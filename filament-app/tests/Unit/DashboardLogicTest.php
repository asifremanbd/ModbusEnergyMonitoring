<?php

namespace Tests\Unit;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_is_online_calculation()
    {
        // Gateway seen recently should be online
        $onlineGateway = Gateway::factory()->create([
            'last_seen_at' => now(),
            'poll_interval' => 10,
        ]);

        $this->assertTrue($onlineGateway->is_online);

        // Gateway not seen for a while should be offline
        $offlineGateway = Gateway::factory()->create([
            'last_seen_at' => now()->subMinutes(5),
            'poll_interval' => 10,
        ]);

        $this->assertFalse($offlineGateway->is_online);

        // Gateway with no last_seen_at should be offline
        $neverSeenGateway = Gateway::factory()->create([
            'last_seen_at' => null,
        ]);

        $this->assertFalse($neverSeenGateway->is_online);
    }

    public function test_gateway_success_rate_calculation()
    {
        $gateway = Gateway::factory()->create([
            'success_count' => 80,
            'failure_count' => 20,
        ]);

        $this->assertEquals(80.0, $gateway->success_rate);

        // Test with no attempts
        $noAttemptsGateway = Gateway::factory()->create([
            'success_count' => 0,
            'failure_count' => 0,
        ]);

        $this->assertEquals(0.0, $noAttemptsGateway->success_rate);

        // Test with only successes
        $perfectGateway = Gateway::factory()->create([
            'success_count' => 100,
            'failure_count' => 0,
        ]);

        $this->assertEquals(100.0, $perfectGateway->success_rate);
    }

    public function test_reading_quality_scopes()
    {
        $dataPoint = DataPoint::factory()->create();

        $goodReading = Reading::factory()->create([
            'data_point_id' => $dataPoint->id,
            'quality' => 'good',
        ]);

        $badReading = Reading::factory()->create([
            'data_point_id' => $dataPoint->id,
            'quality' => 'bad',
        ]);

        $uncertainReading = Reading::factory()->create([
            'data_point_id' => $dataPoint->id,
            'quality' => 'uncertain',
        ]);

        $goodReadings = Reading::goodQuality()->get();
        $this->assertCount(1, $goodReadings);
        $this->assertEquals($goodReading->id, $goodReadings->first()->id);

        $this->assertTrue($goodReading->is_good_quality);
        $this->assertFalse($badReading->is_good_quality);
        $this->assertFalse($uncertainReading->is_good_quality);
    }

    public function test_recent_readings_scope()
    {
        $dataPoint = DataPoint::factory()->create();

        $recentReading = Reading::factory()->create([
            'data_point_id' => $dataPoint->id,
            'read_at' => now()->subMinutes(30),
        ]);

        $oldReading = Reading::factory()->create([
            'data_point_id' => $dataPoint->id,
            'read_at' => now()->subHours(2),
        ]);

        $recentReadings = Reading::recent(60)->get();
        $this->assertCount(1, $recentReadings);
        $this->assertEquals($recentReading->id, $recentReadings->first()->id);
    }

    public function test_data_point_enabled_scope()
    {
        $gateway = Gateway::factory()->create();

        $enabledPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        $disabledPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => false,
        ]);

        $enabledPoints = DataPoint::enabled()->get();
        $this->assertCount(1, $enabledPoints);
        $this->assertEquals($enabledPoint->id, $enabledPoints->first()->id);
    }

    public function test_gateway_active_scope()
    {
        $activeGateway = Gateway::factory()->create([
            'is_active' => true,
        ]);

        $inactiveGateway = Gateway::factory()->create([
            'is_active' => false,
        ]);

        $activeGateways = Gateway::active()->get();
        $this->assertCount(1, $activeGateways);
        $this->assertEquals($activeGateway->id, $activeGateways->first()->id);
    }

    public function test_reading_display_value_formatting()
    {
        $reading = Reading::factory()->create([
            'scaled_value' => 123.456789,
        ]);

        $this->assertEquals('123.46', $reading->display_value);

        $nullReading = Reading::factory()->create([
            'scaled_value' => null,
        ]);

        $this->assertEquals('N/A', $nullReading->display_value);
    }

    public function test_data_point_register_calculations()
    {
        $dataPoint = DataPoint::factory()->create([
            'register_address' => 100,
            'register_count' => 4,
        ]);

        $this->assertEquals(103, $dataPoint->register_end_address);
    }

    public function test_data_point_modbus_function_checks()
    {
        $inputRegisterPoint = DataPoint::factory()->create([
            'modbus_function' => 4,
        ]);

        $holdingRegisterPoint = DataPoint::factory()->create([
            'modbus_function' => 3,
        ]);

        $this->assertTrue($inputRegisterPoint->is_input_register);
        $this->assertFalse($inputRegisterPoint->is_holding_register);

        $this->assertTrue($holdingRegisterPoint->is_holding_register);
        $this->assertFalse($holdingRegisterPoint->is_input_register);
    }

    public function test_gateway_relationships()
    {
        $gateway = Gateway::factory()->create();
        $dataPoint = DataPoint::factory()->create(['gateway_id' => $gateway->id]);
        $reading = Reading::factory()->create(['data_point_id' => $dataPoint->id]);

        $this->assertInstanceOf(DataPoint::class, $gateway->dataPoints->first());
        $this->assertInstanceOf(Reading::class, $gateway->readings->first());
        $this->assertEquals($gateway->id, $dataPoint->gateway->id);
        $this->assertEquals($dataPoint->id, $reading->dataPoint->id);
    }

    public function test_reading_is_recent_calculation()
    {
        $gateway = Gateway::factory()->create([
            'poll_interval' => 10,
        ]);

        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
        ]);

        $recentReading = Reading::factory()->create([
            'data_point_id' => $dataPoint->id,
            'read_at' => now(),
        ]);

        $oldReading = Reading::factory()->create([
            'data_point_id' => $dataPoint->id,
            'read_at' => now()->subMinutes(2),
        ]);

        $this->assertTrue($recentReading->is_recent);
        $this->assertFalse($oldReading->is_recent);
    }

    public function test_data_point_latest_reading_attribute()
    {
        $dataPoint = DataPoint::factory()->create();

        $olderReading = Reading::factory()->create([
            'data_point_id' => $dataPoint->id,
            'read_at' => now()->subMinutes(10),
        ]);

        $latestReading = Reading::factory()->create([
            'data_point_id' => $dataPoint->id,
            'read_at' => now(),
        ]);

        $this->assertEquals($latestReading->id, $dataPoint->latest_reading->id);
    }
}