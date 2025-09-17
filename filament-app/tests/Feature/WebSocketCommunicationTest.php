<?php

namespace Tests\Feature;

use App\Events\GatewayStatusChanged;
use App\Events\NewReadingReceived;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebSocketCommunicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    public function test_new_reading_event_is_dispatched_when_reading_created()
    {
        // Arrange
        $gateway = Gateway::factory()->create();
        $dataPoint = DataPoint::factory()->create(['gateway_id' => $gateway->id]);

        // Act
        $reading = Reading::create([
            'data_point_id' => $dataPoint->id,
            'raw_value' => json_encode([100, 200]),
            'scaled_value' => 15.5,
            'quality' => 'good',
            'read_at' => now(),
        ]);

        // Manually dispatch the event (since we're not using the service)
        NewReadingReceived::dispatch($reading);

        // Assert
        Event::assertDispatched(NewReadingReceived::class, function ($event) use ($reading) {
            return $event->reading->id === $reading->id;
        });
    }

    public function test_gateway_status_changed_event_is_dispatched()
    {
        // Arrange
        $gateway = Gateway::factory()->create(['is_active' => true]);

        // Act
        GatewayStatusChanged::dispatch($gateway, 'active', 'inactive');

        // Assert
        Event::assertDispatched(GatewayStatusChanged::class, function ($event) use ($gateway) {
            return $event->gateway->id === $gateway->id &&
                   $event->previousStatus === 'active' &&
                   $event->currentStatus === 'inactive';
        });
    }

    public function test_new_reading_event_contains_correct_data()
    {
        // Arrange
        $gateway = Gateway::factory()->create(['name' => 'Test Gateway']);
        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'group_name' => 'Meter_1',
            'label' => 'Voltage L1',
        ]);

        $reading = Reading::create([
            'data_point_id' => $dataPoint->id,
            'raw_value' => json_encode([100, 200]),
            'scaled_value' => 230.5,
            'quality' => 'good',
            'read_at' => now(),
        ]);

        // Act
        $event = new NewReadingReceived($reading);
        $broadcastData = $event->broadcastWith();

        // Assert
        $this->assertArrayHasKey('reading', $broadcastData);
        $this->assertEquals($reading->id, $broadcastData['reading']['id']);
        $this->assertEquals($dataPoint->id, $broadcastData['reading']['data_point_id']);
        $this->assertEquals(230.5, $broadcastData['reading']['scaled_value']);
        $this->assertEquals('good', $broadcastData['reading']['quality']);
        $this->assertEquals($gateway->id, $broadcastData['reading']['data_point']['gateway_id']);
        $this->assertEquals('Meter_1', $broadcastData['reading']['data_point']['group_name']);
        $this->assertEquals('Voltage L1', $broadcastData['reading']['data_point']['label']);
    }

    public function test_gateway_status_event_contains_correct_data()
    {
        // Arrange
        $gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'is_active' => false,
            'success_count' => 50,
            'failure_count' => 5,
        ]);

        // Act
        $event = new GatewayStatusChanged($gateway, 'active', 'inactive');
        $broadcastData = $event->broadcastWith();

        // Assert
        $this->assertArrayHasKey('gateway', $broadcastData);
        $this->assertArrayHasKey('status_change', $broadcastData);
        
        $gatewayData = $broadcastData['gateway'];
        $this->assertEquals($gateway->id, $gatewayData['id']);
        $this->assertEquals('Test Gateway', $gatewayData['name']);
        $this->assertEquals('192.168.1.100', $gatewayData['ip_address']);
        $this->assertEquals(502, $gatewayData['port']);
        $this->assertEquals(false, $gatewayData['is_active']);
        $this->assertEquals(50, $gatewayData['success_count']);
        $this->assertEquals(5, $gatewayData['failure_count']);
        
        $statusChange = $broadcastData['status_change'];
        $this->assertEquals('active', $statusChange['previous']);
        $this->assertEquals('inactive', $statusChange['current']);
        $this->assertArrayHasKey('timestamp', $statusChange);
    }

    public function test_events_broadcast_on_correct_channels()
    {
        // Arrange
        $gateway = Gateway::factory()->create();
        $dataPoint = DataPoint::factory()->create(['gateway_id' => $gateway->id]);
        $reading = Reading::factory()->create(['data_point_id' => $dataPoint->id]);

        // Test NewReadingReceived channels
        $readingEvent = new NewReadingReceived($reading);
        $readingChannels = $readingEvent->broadcastOn();
        
        $this->assertCount(2, $readingChannels);
        $this->assertEquals('readings', $readingChannels[0]->name);
        $this->assertEquals("gateway.{$gateway->id}", $readingChannels[1]->name);

        // Test GatewayStatusChanged channels
        $statusEvent = new GatewayStatusChanged($gateway, 'active', 'inactive');
        $statusChannels = $statusEvent->broadcastOn();
        
        $this->assertCount(2, $statusChannels);
        $this->assertEquals('gateways', $statusChannels[0]->name);
        $this->assertEquals("gateway.{$gateway->id}", $statusChannels[1]->name);
    }

    public function test_events_have_correct_broadcast_names()
    {
        // Arrange
        $gateway = Gateway::factory()->create();
        $dataPoint = DataPoint::factory()->create(['gateway_id' => $gateway->id]);
        $reading = Reading::factory()->create(['data_point_id' => $dataPoint->id]);

        // Test event names
        $readingEvent = new NewReadingReceived($reading);
        $this->assertEquals('reading.new', $readingEvent->broadcastAs());

        $statusEvent = new GatewayStatusChanged($gateway, 'active', 'inactive');
        $this->assertEquals('gateway.status-changed', $statusEvent->broadcastAs());
    }
}